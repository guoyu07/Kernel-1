<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午2:55
 */

namespace Kernel\Components\Backstage;

use Kernel\Components\Process\Process;
use Kernel\CoreBase\SwooleException;

class BackstageProcess extends Process
{
    /**
     * @param $process
     * @throws SwooleException
     */
    public function start($process)
    {
        $path = $this->config->get("backstage.bin_path", false);
        if ($path == false) {
            $path = BIN_DIR . "/exec/backstage";
        } else {
            $path = MYROOT . $path;
        }
        if (!is_file($path)) {
            secho("Backstage", "后台监控没有安装,如需要请联系白猫获取（需VIP客户）,或者将backstage.php配置中enable关闭");
            get_instance()->server->shutdown();
            exit();
        }

        $this->exec($path, [$this->config->get("backstage.port"), $this->config->get("backstage.websocket_port")]);
    }

    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}
