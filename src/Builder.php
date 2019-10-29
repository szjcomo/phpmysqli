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

/**
 * 构造查询的sql语句
 */
class Builder 
{
    // 查询表达式映射
    protected static $exp = ['EQ' => '=', 'NEQ' => '<>', 'GT' => '>', 'EGT' => '>=', 'LT' => '<', 'ELT' => '<=', 'NOTLIKE' => 'NOT LIKE', 'NOTIN' => 'NOT IN', 'NOTBETWEEN' => 'NOT BETWEEN', 'NOTEXISTS' => 'NOT EXISTS', 'NOTNULL' => 'NOT NULL', 'NOTBETWEEN TIME' => 'NOT BETWEEN TIME'];
    // SQL表达式
    protected static $selectSql 	= 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';
    protected static $insertSql 	= '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';
    protected static $insertAllSql 	= '%INSERT% INTO %TABLE% (%FIELD%) VALUES %DATA% %COMMENT%';
    protected static $updateSql 	= 'UPDATE %TABLE% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';
    protected static $deleteSql 	= 'DELETE FROM %TABLE%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';
    // 查询表达式解析
    protected static $parser = [
        'parseCompare'     => ['=', '<>', '>', '>=', '<', '<='],
        'parseLike'        => ['LIKE', 'NOT LIKE'],
        'parseBetween'     => ['NOT BETWEEN', 'BETWEEN'],
        'parseIn'          => ['NOT IN', 'IN'],
        'parseExp'         => ['EXP'],
        'parseRegexp'      => ['REGEXP', 'NOT REGEXP'],
        'parseNull'        => ['NOT NULL', 'NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime'        => ['< TIME', '> TIME', '<= TIME', '>= TIME'],
        'parseExists'      => ['NOT EXISTS', 'EXISTS'],
        'parseColumn'      => ['COLUMN'],
    ];

    /**
     * 字段和表名处理
     * @access public
     * @param  Query     $query 查询对象
     * @param  mixed     $key   字段名
     * @param  bool      $strict   严格检测
     * @return string
     */
    public static function parseKey(Mysqli $query, $key, $strict = false) 
    {
        if (is_numeric($key)) {
            return $key;
        } elseif ($key instanceof Expression) {
            return $key->getValue();
        }
        $key = trim($key);
        if (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]/', $key)) {
            list($table, $key) = explode('.', $key, 2);
            $alias = $query->getOptions('alias');
            if ('__TABLE__' == $table) {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }
            if (isset($alias[$table])) {
                $table = $alias[$table];
            }
        }
        if ('*' != $key && !preg_match('/[,\'\"\*\(\)`.\s]/', $key)) {
            $key = '`' . $key . '`';
        }
        if (isset($table)) {
            if (strpos($table, '.')) {
                $table = str_replace('.', '`.`', $table);
            }
            $key = '`' . $table . '`.' . $key;
        }
        return $key;
    }


    /**
     * [parseRegexp 正则查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query        $query        查询对象
     * @param  string       $key
     * @param  string       $exp
     * @param  mixed        $value
     * @param  string       $field
     * @return string
     */
    protected static function parseRegexp(Mysqli $query, $key, $exp, $value, $field) 
    {
        if ($value instanceof Expression) {
            $value = $value->getValue();
        }
        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * [parseDataBind 数据绑定处理]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query     查询对象
     * @param  string    $key       字段名
     * @param  mixed     $data      数据
     * @param  array     $bind      绑定数据
     * @return string
     */
    protected static function parseDataBind(Mysqli $query, $key, $data, $bind = [])
    {
        if ($data instanceof Expression) return $data->getValue();
        $name = $query->bind($data, isset($bind[$key]) ? $bind[$key] : \PDO::PARAM_STR);
        return $name;
    }


    /**
     * [parseField field分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query     查询对象
     * @param  mixed     $fields    字段名
     * @return string
     */
    protected static function parseField(Mysqli $query, $fields)
    {
        if ('*' == $fields || empty($fields)) {
            $fieldsStr = '*';
        } elseif (is_array($fields)) {
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];
            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = self::parseKey($query, $key) . ' AS ' . self::parseKey($query, $field, true);
                } else {
                    $array[] = self::parseKey($query, $field);
                }
            }
            $fieldsStr = implode(',', $array);
        }
        return $fieldsStr;
    }
    /**
     * [parseWhere where分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query   查询对象
     * @param  mixed     $where   查询条件
     * @return string
     */
    protected static function parseWhere(Mysqli $query, $where)
    {
        $whereStr = self::buildWhere($query, $where);
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }
    /**
     * [buildWhere 生成查询条件SQL]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  Query     $query     查询对象
     * @param  mixed     $where     查询条件
     * @return string
     */
    public static function buildWhere(Mysqli $query, $where) 
    {
    	//print_r($where);die;
        if (empty($where)) {
            $where = [];
        }
        $whereStr = '';
        $binds    = [];
        foreach ($where as $logic => $val) {
            $str = [];
            foreach ($val as $value) {
                if ($value instanceof Expression) {
                    $str[] = ' ' . $logic . ' ( ' . $value->getValue() . ' )';
                    continue;
                }
                if (is_array($value)) {
                    if (key($value) !== 0) {
                        throw new \Exception('where express error:' . var_export($value, true));
                    }
                    $field = array_shift($value);
                } elseif (!($value instanceof \Closure)) {
                    throw new \Exception('where express error:' . var_export($value, true));
                }
                if (is_array($field)) {
                    array_unshift($value, $field);
                    $str2 = [];
                    foreach ($value as $item) {
                        $str2[] = self::parseBuildWhereItem($query, array_shift($item), $item, $logic, $binds);
                    }
                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $str2) . ' )';
                } else {
                    // 对字段使用表达式查询
                    $field = is_string($field) ? $field : '';
                    $str[] = ' ' . $logic . ' ' . self::parseBuildWhereItem($query, $field, $value, $logic, $binds);
                }
            }
            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($logic) + 1) : implode(' ', $str);
        }
        return $whereStr;
    }
    /**
     * [parseBuildWhereItem where子单元分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @param    Query      $query [description]
     * @param    [type]     $field [description]
     * @param    [type]     $val   [description]
     * @param    string     $rule  [description]
     * @param    array      $binds [description]
     * @return   [type]            [description]
     */
    protected static function parseBuildWhereItem(Mysqli $query, $field, $val, $rule = '', $binds = [])
    {
        // 字段分析
        $key = $field ? self::parseKey($query, $field, true) : '';
        // 查询规则和条件
        if (!is_array($val)) {
            $val = is_null($val) ? ['NULL', ''] : ['=', $val];
        }
        list($exp, $value) = $val;
        // 对一个字段使用多个查询条件
        if (is_array($exp)) {
            $item = array_pop($val);
            // 传入 or 或者 and
            if (is_string($item) && in_array($item, ['AND', 'and', 'OR', 'or'])) {
                $rule = $item;
            } else {
                array_push($val, $item);
            }
            foreach ($val as $k => $item) {
                $str[] = self::parseBuildWhereItem($query, $field, $item, $rule, $binds);
            }
            return '( ' . implode(' ' . $rule . ' ', $str) . ' )';
        }
        // 检测操作符
        $exp = strtoupper($exp);
        if (isset(self::$exp[$exp])) {
            $exp = self::$exp[$exp];
        }
        if ($value instanceof Expression) {
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            // 对象数据写入
            $value = $value->__toString();
        }
        $bindType = isset($binds[$field]) ? $binds[$field] : \PDO::PARAM_STR;
        if (is_scalar($value) && !in_array($exp, ['EXP', 'NOT NULL', 'NULL', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN']) && strpos($exp, 'TIME') === false) {
            if (0 === strpos($value, ':') && $query->isBind(substr($value, 1))) {
            } else {
                $name  = $query->bind($value, $bindType);
                $value = $name;
            }
        }
        // 解析查询表达式
        foreach (self::$parser as $fun => $parse) {
            if (in_array($exp, $parse)) {
                $whereStr = self::$fun($query, $key, $exp, $value, $field, $bindType, isset($val[2]) ? $val[2] : 'AND');
                break;
            }
        }
        if (!isset($whereStr)) {
            throw new \Exception('where express error:' . $exp);
        }
        return $whereStr;
    }

    /**
     * [parseLike 模糊查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @param  string    $logic
     * @return string
     */
    protected static function parseLike(Mysqli $query, $key, $exp, $value, $field, $bindType, $logic)
    {
        // 模糊匹配
        if (is_array($value)) {
            foreach ($value as $item) {
                $name    = $query->bind($item, $bindType);
                $array[] = $key . ' ' . $exp . ' :' . $name;
            }
            $whereStr = '(' . implode(' ' . strtoupper($logic) . ' ', $array) . ')';
        } else {
            $whereStr = $key . ' ' . $exp . ' ' . $value;
        }
        return $whereStr;
    }

    /**
     * [parseColumn 表达式查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query        $query        查询对象
     * @param  string       $key
     * @param  string       $exp
     * @param  array        $value
     * @param  string       $field
     * @param  integer      $bindType
     * @return string
     */
    protected static function parseColumn(Mysqli $query, $key, $exp, array $value, $field, $bindType)
    {
        // 字段比较查询
        list($op, $field2) = $value;
        if (!in_array($op, ['=', '<>', '>', '>=', '<', '<='])) {
            throw new \Exception('where express error:' . var_export($value, true));
        }
        return '( ' . $key . ' ' . $op . ' ' . self::parseKey($query, $field2, true) . ' )';
    }


    /**
     * [parseNull Null查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseNull(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        // NULL 查询
        return $key . ' IS ' . $exp;
    }

    /**
     * 范围查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseBetween(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        // BETWEEN 查询
        $data = is_array($value) ? $value : explode(',', $value);
        $min = $query->bind($data[0], $bindType);
        $max = $query->bind($data[1], $bindType);
        return $key . ' ' . $exp . ' :' . $min . ' AND :' . $max . ' ';
    }


    /**
     * [parseExists Exists查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseExists(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        // EXISTS 查询
        if ($value instanceof \Closure) {
            $value = self::parseClosure($query, $value, false);
        } elseif ($value instanceof Expression) {
            $value = $value->getValue();
        } else {
            throw new \Exception('where express error:' . $value);
        }
        return $exp . ' (' . $value . ')';
    }

    /**
     * [parseTime 时间比较查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseTime(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        return $key . ' ' . substr($exp, 0, 2) . ' ' . self::parseDateTime($query, $value, $field, $bindType);
    }

    /**
     * [parseCompare 大小比较查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseCompare(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        if (is_array($value)) {
            throw new \Exception('where express error:' . $exp . var_export($value, true));
        }
        // 比较运算
        if ($value instanceof \Closure) {
            $value = self::parseClosure($query, $value);
        }
        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * [parseBetweenTime 时间范围查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseBetweenTime(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return $key . ' ' . substr($exp, 0, -4)
        . self::parseDateTime($query, $value[0], $field, $bindType)
        . ' AND '
        . self::parseDateTime($query, $value[1], $field, $bindType);
    }

    /**
     * [parseIn IN查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseIn(Mysqli $query, $key, $exp, $value, $field, $bindType)
    {
        // IN 查询
        if ($value instanceof \Closure) {
            $value = self::parseClosure($query, $value, false);
        } elseif ($value instanceof Expression) {
            $value = $value->getValue();
        } else {
            $value = array_unique(is_array($value) ? $value : explode(',', $value));
            $array = [];
            foreach ($value as $k => $v) {
                $name    = $query->bind($v, $bindType);
                $array[] = ':' . $name;
            }
            $zone = implode(',', $array);
            $value = empty($zone) ? "''" : $zone;
        }
        return $key . ' ' . $exp . ' (' . $value . ')';
    }

    /**
     * [parseClosure 闭包子查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  \Closure  $call
     * @param  bool      $show
     * @return string
     */
    protected static function parseClosure(Mysqli $query, $call, $show = true)
    {
    	throw new \Exception("暂时不支持闭包子查询");
    }

    /**
     * [parseDateTime 日期时间条件解析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $value
     * @param  string    $key
     * @param  integer   $bindType
     * @return string
     */
    protected static function parseDateTime(Mysqli $query, $value, $key, $bindType = null)
    {
        $options = $query->getOptions();
        // 获取时间字段类型
        if (strpos($key, '.')) {
            list($table, $key) = explode('.', $key);
            if (isset($options['alias']) && $pos = array_search($table, $options['alias'])) {
                $table = $pos;
            }
        } else {
            $table = $options['table'];
        }
        $name = $query->bind($value, $bindType);
        return ':' . $name;
    }

    /**
     * [parseLimit limit分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $limit
     * @return string
     */
    protected static function parseLimit(Mysqli $query, $limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * [parseOrder order分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $order
     * @return string
     */
    protected static function parseOrder(Mysqli $query, $order)
    {
        foreach ($order as $key => $val) {
            if ($val instanceof Expression) {
                $array[] = $val->getValue();
            } elseif (is_array($val) && preg_match('/^[\w\.]+$/', $key)) {
                $array[] = self::parseOrderField($query, $key, $val);
            } elseif (is_string($val)) {
                if (is_numeric($key)) {
                    list($key, $sort) = explode(' ', strpos($val, ' ') ? $val : $val . ' ');
                } else {
                    $sort = $val;
                }
                if (preg_match('/^[\w\.]+$/', $key)) {
                    $sort    = strtoupper($sort);
                    $sort    = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';
                    $array[] = self::parseKey($query, $key, true) . $sort;
                } else {
                    throw new \Exception('order express error:' . $key);
                }
            }
        }
        return empty($array) ? '' : ' ORDER BY ' . implode(',', $array);
    }

    /**
     * [parseOrderField orderField分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $key
     * @param  array     $val
     * @return string
     */
    protected static function parseOrderField(Mysqli $query, $key, $val)
    {
        if (isset($val['sort'])) {
            $sort = $val['sort'];
            unset($val['sort']);
        } else {
            $sort = '';
        }
        $sort = strtoupper($sort);
        $sort = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';
        $options = $query->getOptions();
        $bind    = [];
        foreach ($val as $k => $item) {
            $val[$k] = self::parseDataBind($query, $key, $item, $bind);
        }
        return 'field(' . self::parseKey($query, $key, true) . ',' . implode(',', $val) . ')' . $sort;
    }

    /**
     * [parseGroup group分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $group
     * @return string
     */
    protected static function parseGroup(Mysqli $query, $group)
    {
        if (empty($group)) {
            return '';
        }
        if (is_string($group)) {
            $group = explode(',', $group);
        }
        foreach ($group as $key) {
            $val[] = self::parseKey($query, $key);
        }
        return ' GROUP BY ' . implode(',', $val);
    }
    /**
     * [parseHaving having分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $having
     * @return string
     */
    protected static function parseHaving(Mysqli $query, $having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * [parseTable table分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query         查询对象
     * @param  mixed     $tables        表名
     * @return string
     */
    protected static function parseTable(Mysqli $query, $tables)
    {
        $item    = [];
        $options = $query->getOptions();
        foreach ((array) $tables as $key => $table) {
            if (!is_numeric($key)) {
                $key    = self::parseSqlTable($key,$query);
                $item[] = self::parseKey($query, $key) . ' ' . self::parseKey($query, $table);
            } else {
                $table = self::parseSqlTable($table,$query);
                if (isset($options['alias'][$table])) {
                    $item[] = self::parseKey($query, $table) . ' ' . self::parseKey($query, $options['alias'][$table]);
                } else {
                    $item[] = self::parseKey($query, $table);
                }
            }
        }
        return implode(',', $item);
    }

    /**
     * [parseDistinct distinct分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $distinct
     * @return string
     */
    protected static function parseDistinct(Mysqli $query, $distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }
    /**
     * [parseJoin json分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @param    Mysqli $query [description]
     * @param    [type]       $join  [description]
     * @return   [type]              [description]
     */
    protected static function parseJoin(Mysqli $query,$join)
    {
        $joinStr = '';
        if (!empty($join)) {
            foreach ($join as $item) {
                list($table, $type, $on) = $item;
                $condition = [];
                foreach ((array) $on as $val) {
                    if ($val instanceof Expression) {
                        $condition[] = $val->getValue();
                    } elseif (strpos($val, '=')) {
                        list($val1, $val2) = explode('=', $val, 2);
                        $condition[] = self::parseKey($query, $val1) . '=' . self::parseKey($query, $val2);
                    } else {
                        $condition[] = $val;
                    }
                }
                $table = self::parseTable($query, $table);
                $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . implode(' AND ', $condition);
            }
        }
        return $joinStr;
    }

    /**
     * [parseUnion union分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $union
     * @return string
     */
    protected static function parseUnion(Mysqli $query, $union)
    {
        if (empty($union)) {
            return '';
        }
        $type = $union['type'];
        unset($union['type']);
        foreach ($union as $u) {
            if ($u instanceof \Closure) {
                $sql[] = $type . ' ' . self::parseClosure($query, $u);
            } elseif (is_string($u)) {
                $sql[] = $type . ' ( ' . self::parseSqlTable($u) . ' )';
            }
        }
        return ' ' . implode(' ', $sql);
    }

    /**
     * [parseSqlTable 将SQL语句中的__TABLE_NAME__字符串替换成带前缀的表名（小写）]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $sql sql语句
     * @return string
     */
    public static function parseSqlTable($sql,Mysqli $query = null)
    {
        if (false !== strpos($sql, '__')) {
            $sql = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use(&$query) {
                return $query->prefix . strtolower($match[1]);
            }, $sql);
        }
        return $sql;
    }

    /**
     * [parseForce index分析，可在操作链中指定需要强制使用的索引]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $index
     * @return string
     */
    protected static function parseForce(Mysqli $query, $index)
    {
        if (empty($index)) return '';
        return sprintf(" FORCE INDEX ( %s ) ", is_array($index) ? implode(',', $index) : $index);
    }

    /**
     * [parseLock 设置锁机制]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query         $query        查询对象
     * @param  bool|string   $lock
     * @return string
     */
    protected static function parseLock(Mysqli $query, $lock = false)
    {
        if (is_bool($lock)) {
            return $lock ? ' FOR UPDATE ' : '';
        } elseif (is_string($lock) && !empty($lock)) {
            return ' ' . trim($lock) . ' ';
        }
    }

    /**
     * [parseComment comment分析]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $comment
     * @return string
     */
    protected static function parseComment(Mysqli $query, $comment)
    {
        if (false !== strpos($comment, '*/')) {
            $comment = strstr($comment, '*/', true);
        }
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * [getFieldsBind 获取数据表绑定信息]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @access public
     * @param  string $tableName 数据表名
     * @return array
     */
    public static function getFieldsBind($tableName)
    {
        return self::getTableInfo($tableName, 'bind');
    }

    /**
     * [parseData 数据分析]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @access protected
     * @param  Query     $query     查询对象
     * @param  array     $data      数据
     * @param  array     $fields    字段信息
     * @param  array     $bind      参数绑定
     * @return array
     */
    protected static function parseData(Mysqli $query, $data = [], $fields = [], $bind = [])
    {
 		if (empty($data)) return [];
        $options = $query->getOptions();
        $result = [];
        foreach ($data as $key => $val) {
            $item = self::parseKey($query, $key, true);
            if ($val instanceof Expression) {
                $result[$item] = $val->getValue();
                continue;
            } elseif (is_object($val) && method_exists($val, '__toString')) {
                // 对象数据写入
                $val = $val->__toString();
            }
            if (is_null($val)) {
                $result[$item] = 'NULL';
            } elseif (is_array($val) && !empty($val)) {
                switch (strtoupper($val[0])) {
                    case 'INC':
                        $result[$item] = $item . ' + ' . floatval($val[1]);
                        break;
                    case 'DEC':
                        $result[$item] = $item . ' - ' . floatval($val[1]);
                        break;
                    case 'EXP':
                        throw new \Exception('not support data:[' . $val[0] . ']');
                }
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                $result[$item] = self::parseDataBind($query, $key, $val, $bind);
            }
        }
        return $result;
    }


    /**
     * [select 生成查询SQL]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  Query  $query  查询对象
     * @return string
     */
    public static function select(Mysqli $query)
    {
        $options = $query->getOptions();
        //print_r($options);die;
        $sql =  str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                self::parseTable($query, $options['table']),
                self::parseDistinct($query, $options['distinct']),
                self::parseField($query, $options['field']),
                self::parseJoin($query, $options['join']),
                self::parseWhere($query, $options['where']),
                self::parseGroup($query, $options['group']),
                self::parseHaving($query, $options['having']),
                self::parseOrder($query, $options['order']),
                self::parseLimit($query, $options['limit']),
                self::parseUnion($query, $options['union']),
                self::parseLock($query, $options['lock']),
                self::parseComment($query, $options['comment']),
                self::parseForce($query, $options['force']),
            ],
            self::$selectSql);
        return $sql;
    }

    /**
     * [insert 生成Insert SQL]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @access public
     * @param  Query     $query   查询对象
     * @param  bool      $replace 是否replace
     * @return string
     */
    public static function insert(Mysqli $query, $replace = false)
    {
        $options = $query->getOptions();
        //print_r($options);die;
        // 分析并处理数据
        $data = self::parseData($query, $options['data']);
        if (empty($data)) throw new \Exception('There is no data to insert, please check the parameters...');
        $fields = array_keys($data);
        $values = array_values($data);
        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                self::parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                self::parseComment($query, $options['comment']),
            ],
            self::$insertSql);
    }

    /**
     * [insertAll 生成insertall SQL]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @access public
     * @param  Query     $query   查询对象
     * @param  array     $dataSet 数据集
     * @param  bool      $replace 是否replace
     * @return string
     */
    public static function insertAll(Mysqli $query, $dataSet, $replace = false)
    {
        $options = $query->getOptions();
        // 获取绑定信息
        $bind = [];
        foreach ($dataSet as $data) {
            $data = self::parseData($query, $data);
            $values[] = '(' . implode(',', array_values($data)) .')';
            if (!isset($insertFields)) {
                $insertFields = array_keys($data);
            }
        }
        $fields = [];
        foreach ($insertFields as $field) {
            $fields[] = self::parseKey($query, $field);
        }
        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                self::parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(',', $values),
                self::parseComment($query, $options['comment']),
            ],
            self::$insertAllSql);
    }

    /**
     * 生成update SQL
     * @access public
     * @param  Query     $query  查询对象
     * @return string
     */
    public static function update(Mysqli $query)
    {
        $options = $query->getOptions();
        $data = self::parseData($query, $options['data']);
        if (empty($data)) throw new \Exception('There is no data to update, please check the parameters...');
        if (empty($options['where'])) throw new \Exception('Update condition must be passed in when updating');
        foreach ($data as $key => $val) {
            $set[] = $key . ' = ' . $val;
        }
        return str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                self::parseTable($query, $options['table']),
                implode(' , ', $set),
                self::parseJoin($query, $options['join']),
                self::parseWhere($query, $options['where']),
                self::parseOrder($query, $options['order']),
                self::parseLimit($query, $options['limit']),
                self::parseLock($query, $options['lock']),
                self::parseComment($query, $options['comment']),
            ],
            self::$updateSql);
    }

    /**
     * 生成delete SQL
     * @access public
     * @param  Query  $query  查询对象
     * @return string
     */
    public static function delete(Mysqli $query)
    {
        $options = $query->getOptions();
        if (empty($options['where'])) throw new \Exception('Delete condition must be passed in when deleting');
        return str_replace(
            ['%TABLE%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                self::parseTable($query, $options['table']),
                !empty($options['using']) ? ' USING ' . self::parseTable($query, $options['using']) . ' ' : '',
                self::parseJoin($query, $options['join']),
                self::parseWhere($query, $options['where']),
                self::parseOrder($query, $options['order']),
                self::parseLimit($query, $options['limit']),
                self::parseLock($query, $options['lock']),
                self::parseComment($query, $options['comment']),
            ],
            self::$deleteSql);
    }

}