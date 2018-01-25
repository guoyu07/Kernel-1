<?php
namespace Kernel;

class Monitor
{
    protected $monitorname;
    protected $filename;

    public function __construct($monitorname, $filename)
    {
        $this->monitorname = $monitorname;
        $this->filename = $filename;
    }

    public function outPutWebStatus()
    {
        $this->outputStatus($this->getNowStatus(), '<br/>');
    }

    public function outPutNowStatus()
    {
        $this->outputStatus($this->getNowStatus());
    }

    public function getNowStatus()
    {
        exec('ps axu|grep '.$this->monitorname, $output);

        $output = $this->packExeData($output);
        $pidDetail = ServerPid::getPidList($this->filename);

        $pidList = [];
        foreach ($pidDetail as $key => $value) {
            foreach ($value as $k => $v) {
                $pid = $v['pid'];
                $pidList[$pid] = [
                    'type' => $key,
                    'name' => $k,
                    'startDate'=>$v['datetime'],
                ];
            }
        }
        $pidDetail = [];
        foreach ($output as $key => $value) {
            if (!empty($pidList[$value[1]])) {
                $value[] = $pidList[$value[1]]['type'];
                $value[] = $pidList[$value[1]]['name'];
                $value[] = $pidList[$value[1]]['startDate'];
                $pidDetail[] = $value;
            }
        }

        return $pidDetail;
    }



    public function outputStatus($pidDetail, $explode = "\n")
    {
        echo "Welcome ".$this->monitorname." !".$explode;
        $pidStatic = [];
        foreach ($pidDetail as $key => $value) {
            if (empty($pidStatic[$value[11]])) {
                $pidStatic[$value[11]] = 1;
            } else {
                $pidStatic[$value[11]] ++;
            }
        }
        foreach ($pidStatic as $key => $value) {
            echo ucfirst($key)." Process Num:".$value.$explode;
        }

        echo "------------------------------PROCESS STATUS----------------------------------".$explode;
        echo "Type        Pid         %CPU        %MEM        MEM         Start                   Name ".$explode;
        foreach ($pidDetail as $key => $value) {
            echo str_pad($value[11], 12).
            str_pad($value[1], 12).
            str_pad($value[2], 12).
            str_pad($value[3], 12).
            str_pad(round($value[5]/1024, 2)."M", 12).
            str_pad($value[13], 24)
            .$value[12]
            .$explode;
        }
    }

    protected function getSymbolByQuantity($bytes)
    {
        $symbols = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $exp = floor(log($bytes)/log(1024));

        return sprintf('%.2f '.$symbols[$exp], ($bytes/pow(1024, floor($exp))));
    }


    protected function packExeData($output)
    {
        $data = [];
        foreach ($output as $key => $value) {
            $data[] = $this->dealSingleData($value);
        }
        return $data;
    }

    protected function dealSingleData($info)
    {
        $data = [];
        $i = 0;
        $num = 0;

        while ($num<=9) {
            $start = '';
            while ($info[$i] != ' ') {
                $start .= $info[$i];
                $i++;
            }
            $data[] = $start;
            while ($info[$i] == ' ') {
                $i++;
            }
            $num++;
        }
        $data[] = substr($info, $i);
        return $data;
    }
}
