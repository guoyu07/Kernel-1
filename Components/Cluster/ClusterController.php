<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-18
 * Time: 上午10:52
 */

namespace Kernel\Components\Cluster;

use Kernel\Components\Event\EventDispatcher;
use Kernel\Components\Process\ProcessManager;
use Kernel\Components\SDHelp\SDHelpProcess;
use Kernel\CoreBase\Child;
use Kernel\Start;

/**
 * 集群控制器
 * Class Cluster
 * @package Kernel\Controllers
 */
class ClusterController extends Child
{
    /**
     * 同步数据
     * @param $node_name
     * @param $datas
     * @param $type
     */
    public function syncNodeData($node_name, $datas, $type)
    {
        $datas = array_values($datas);
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_syncData($node_name, $datas, $type);
    }

    /**
     * 添加数据
     * @param $node_name
     * @param $uid
     */
    public function addNodeUid($node_name, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_addUid($node_name, $uid);
    }

    /**
     * 移除数据
     * @param $node_name
     * @param $uid
     */
    public function removeNodeUid($node_name, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_removeUid($node_name, $uid);
    }

    public function sendToUid($uid, $data)
    {
        getInstance()->sendToUid($uid, $data, true);
    }

    public function sendToUids($uids, $data)
    {
        getInstance()->sendToUids($uids, $data, true);
    }

    public function sendToAll($data)
    {
        getInstance()->sendToAll($data, true);
    }

    public function kickUid($uid)
    {
        getInstance()->kickUid($uid, true);
    }

    public function pub($sub, $data)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_pub($sub, $data);
    }

    public function dispatchEvent($type, $data)
    {
        EventDispatcher::getInstance()->dispatch($type, $data, false, true);
    }

    public function setDebug($bool)
    {
    }

    public function reload()
    {
        getInstance()->server->reload();
    }

    public function status()
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_status();
    }

    /**
     * @param $uid
     * @return mixed|null
     */
    public function getUidInfo($uid)
    {
        $fd = getInstance()->getFdFromUid($uid);
        if (!empty($fd)) {
            $fdInfo = getInstance()->getFdInfo($fd);
            $fdInfo['node'] = getNodeName();
            return $fdInfo;
        } else {
            return [];
        }
    }

    /**
     * @return mixed
     */
    public function getAllSub()
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getAllSub();
        return $result;
    }

    /**
     * 获取统计信息
     * @param $index
     * @param $num
     * @return mixed
     */
    public function getStatistics($index, $num)
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getStatistics($index, $num);
        return $result;
    }

    /**
     * @param $topic
     * @return mixed
     */
    public function getSubMembersCount($topic)
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getSubMembersCount($topic);
        return $result;
    }

    /**
     * @param $topic
     * @return mixed
     */
    public function getSubMembers($topic)
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getSubMembers($topic);
        return $result;
    }

    /**
     * @param $uid
     * @return mixed
     */
    public function getUidTopics($uid)
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getUidTopics($uid);
        return $result;
    }
}
