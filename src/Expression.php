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
 * 查询表达式实例
 */
Class Expression {
    /**
     * 查询表达式
     *
     * @var string
     */
    Protected $value;

    /**
     * 创建一个查询表达式
     *
     * @param  string  $value
     * @return void
     */
    Public function __construct($value){
        $this->value = $value;
    }

    /**
     * 获取表达式
     *
     * @return string
     */
    Public function getValue(){
        return $this->value;
    }
    /**
     * [__toString 转换为字符串]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   string     [description]
     */
    Public function __toString(){
        return (string) $this->value;
    }
}