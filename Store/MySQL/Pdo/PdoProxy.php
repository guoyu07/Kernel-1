<?php

/**
 * 数据库 PDO 操作类
 * @var
 */
namespace Kernel\Store\MySQL\Pdo;

use Kernel\Utilities\Arr;

class PdoProxy
{
    /**
     * 绑定参数
     *
     * @var array
     */
    protected $param = [];


    /**
     * Sql
     *
     * @var string
     */
    protected $sql = "";

    /**
     * @var array
     */
    protected $link; // 配置

    /**
     * 数据库连接串
     *
     * @param $conf
     */
    public function __construct($conf)
    {
        $config = $conf['config'];
        unset($conf);
        $this->link = $this->connect($config);
    }


    /**
     * 数据库链接
     * @param   array $conf
     * @return  连接标识
     */
    private function connect($conf)
    {
        $link = '';
        try {
            $link = new \pdoProxy($conf);
        } catch (Exception $e) {
            throw new \Exception('pdoProxy Connect Error! Code:'.$e->getCode().',ErrorInfo!:'.$e->getMessage().'<br />');
        }
        unset($conf);
        return $link;
    }



    /**
     * 预处理语句
     *
     * @param string $sql
     * @param \PDO $link
     * @param bool $paramesetParams
     *
     * @return \PDOStatement
     */

    public function prepare($sql, $link)
    {
        try {
            $paramesult = $link->prepare($sql);
            unset($sql, $link);
            return $paramesult;
        } catch (\PDOException $e) {
            throw new \Exception('Pdo Prepare Sql error! Code:'.$e->getCode().',ErrorInfo!:'.$e->getMessage().'<br />');
        }
        unset($sql, $link);
        return false;
    }


    /**
     * 执行预处理语句
     *
     * @param object $stmt PDOStatement
     * @param array $param
     * @return bool
     */
    private function execute($stmt, $param = array())
    {
        empty($param) && $param = $this->param;
        $stmt = $this->bind($stmt, $param);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        if (!$stmt->execute()) {
            $error = $stmt->errorInfo();
            unset($param);
            throw new \Exception(var_export($error, true));
            return false;
        }
        return true;
    }

    /**
     * 数据绑定
     * @param   obj $stmt
     * @param   参数 $param array
     * @return
     */
    private function bind($stmt, $param)
    {
        if (is_object($stmt) && ($stmt instanceof \PDOStatement)) {
            if ($param) {
                foreach ($param as $key => $value) {
                    if (is_int($value)) {
                        $paramVal = \PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $paramVal = \PDO::PARAM_BOOL;
                    } elseif (is_null($value)) {
                        $paramVal = \PDO::PARAM_NULL;
                    } elseif (is_string($value)) {
                        $paramVal = \PDO::PARAM_STR;
                    } else {
                        $paramVal = false;
                    }
                    if ($paramVal) {
                        $stmt->bindParam($key, $value, $paramVal);
                    }
                    unset($key, $value, $paramVal);
                }
                unset($param);
            }
        }
        return $stmt;
    }


    /**
     * 返回查询的 sql 语句
     *
     * @return string
     */
    public function toSql()
    {
        $param = $this->param;
        $sql = $this->sql;
        $i = 0;
        $paramet = preg_replace_callback(
            '/:([0-9a-z_]+)|\?+/i',
            function ($m) use ($param, &$i) {
                $k = array_keys($param);
                $val = $m[0] == '?' ? $param[$i] : (substr($k[$i], 0, 1) == ':' ? $param[$m[0]] : $param[$m[1]]);
                $i++;
                if (is_int($val)) {
                    $v = "{$val}";
                    return $v;
                } elseif (is_null($val)) {
                    $v = "NULL";
                    return $v;
                } elseif (is_string($val)) {
                    $v = "'{$val}'";
                    return $v;
                }
            },
            $sql
        );
        return $paramet;
    }


    /**
     * SQL 语句条件组装
     *
     * @param  array $arr; 要组装的数组
     * @return string
     */
    protected function arrToCondition($arr)
    {
        $s = $p = '';
        $params = array();
        foreach ($arr as $k => $v) {
            $p = "`{$k}`= :{$k}";
            $params[':'.$k] = $v;

            $s .= (empty($s) ? '' : ',').$p;
        }
        $this->param = Arr::merge($this->param, $params);
        return $s;
    }



    /**
     * 获取当前 db 所有表名
     *
     * @return array
     */
    public function getTables($dbname)
    {
        $stmt = $this->prepare('SHOW TABLE STATUS FROM '.$dbname, $this->link);
        $this->execute($stmt);
        $result = $stmt->fetchAll();
        unset($dbname);
        return $result;
    }


    /**
     * getFields 获取指定数据表中的全部字段名
     *
     * @param String $table 表名
     * @return array
     */
    private function getFields($table)
    {
        $stmt = $this->prepare('SHOW COLUMNS FROM '.$table, $this->link);
        $this->execute($stmt);
        $result = $stmt->fetchAll();
        unset($table);
        return $result;
    }


    /**
     * 开启事务
     *
     * @return bool
     */
    private function beginTransaction()
    {
        return $this->link->beginTransaction();
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    private function commit()
    {
        return $this->link->commit();
    }

    /**
     * 是否在事物中
     * @return  bool
     */
    private function inTransaction()
    {
        return $this->link->inTransaction();
    }

    /**
     * 回滚事务
     *
     * @param string $paramollBackTo 是否为还原到某个保存点
     *
     * @return bool
     */
    private function rollBack()
    {
        return $this->link->rollBack();
    }

    /**
     * 获取上一 INSERT 的主键值
     *
     * @return int
     */
    private function lastInsertId()
    {
        return $this->link->lastInsertId();
    }


    /**
     * 根据 key 取出数据
     *
     * @param  string $table 表名;
     * @param  string $key   主键值;
     * @param  string $field 字段
     * @return array array('uid'=>123, 'username'=>'abc')/false
     */
    private function get($table, $value, $field = array())
    {
        $fields = '*';
        if (!empty($field) && is_array($field)) {
            $fields = implode(',', $field);
        }
        $this->sql = "SELECT {$fields} FROM {$table} WHERE `id`=:id  Limit 1";
        $this->param = [':id'=>$value];
        unset($table, $value, $field, $fields);
        return $this;
    }


    /**
     * 根据 key 新增 一条数据
     * @param  string $table 表名;
     * @param  array  $data  eg: array('username'=>'admin', 'email'=>'xxx@live.com')
     * @return obj
     */
    private function insert($table, $data)
    {
        if (!is_array($data)) {
            throw new \Exception("data is not array");
        }
        $s = $this->arrToCondition($data);
        $this->sql = "INSERT INTO {$table} SET {$s}";
        unset($table, $data, $s);
        return $this;
    }


    /**
     * 根据 key 更新一条数据
     *
     * @param string $table     eg 'table'
     * @param array  $data      eg: array('username'=>'admin', 'email'=>'xxx@live.com')
     * @param string $where     username=:username and password=:password
     * @param array  $condition array(':username'=>'aaa',':password'=>'xxxxxx');
     *
     * @return boolean
     */
    private function update($table, $data, $where, $condition)
    {
        if (empty($table) || empty($data) || empty($where) || empty($condition)) {
            throw new \Exception("param is null");
        }
        $s = $this->arrToCondition($data);
        $this->param = array_merge($this->param, $condition);
        $this->sql="UPDATE {$table} SET {$s} WHERE {$where}";
        unset($table, $data, $where, $condition, $s);
        return $this;
    }

    /**
     *
     * @param string $table     eg 'table'
     * @param string $where     username=:username and password=:password
     * @param array  $condition array(':username'=>'aaa',':password'=>'xxxxxx');
     *
     * @return boolean
     */
    private function delete($table, $where = null, $condition = [])
    {
        if (empty($table)) {
            return false;
        }
        $sql = "DELETE FROM {$table} ";
        if ($where) {
               $sql .= " WHERE ".$where;
        }
        $this->param = Arr::merge($this->param, $condition);

        $this->sql = $sql;
        return $this;
    }



    /**
     * 通过 Sql 语句获取
     *
     * @param  [string] $sql
     * @param  array    $bindParams
     * @return
     */
    private function query($sql, $bindParams = array())
    {
        $this->param = $bindParams;
        $this->sql = $sql;
        return $this;
    }


    /**
     * 获取所有数据, 以二维数组返回
     * @return array
     */
    private function getAll()
    {
        $stmt = $this->prepare($this->sql, $this->link);
        $this->execute($stmt, $this->param);
        $return = $stmt->fetchAll();
        $this->sql = null;
        $this->param = array();
        unset($this->sql, $this->param, $stmt);
        if (empty($return)) {
            return false;
        }
        return $return;
    }

    /**
     * 获取一条数据 以一维数据返回
     */
    private function getOne()
    {
        $stmt = $this->prepare($this->sql, $this->link);
        $this->execute($stmt, $this->param);
        $return = $stmt->fetch();
        $this->sql = null;
        $this->param = array();
        unset($this->sql, $this->param, $stmt);
        if (empty($return)) {
            return false;
        }
        return $return;
    }




    /**
     * 获取数据返回
     */
    private function getValue()
    {
        $stmt = $this->prepare($this->sql, $this->link);

        $this->execute($stmt, $this->param);
        $return = $stmt->fetchColumn();
        $this->sql = null;
        $this->param = array();
        unset($this->sql, $this->param, $stmt);
        return $return;
    }


    /**
     * 执行 sql 语句
     */
    private function exec()
    {
        $stmt = $this->prepare($this->sql, $this->link);
        $this->execute($stmt, $this->param);
        $this->sql = null;
        $this->param = array();
        unset($this->link, $this->sql, $this->param);
        return  $stmt->rowCount();
    }

    private function release()
    {
        return $this->link->release();
    }
}
