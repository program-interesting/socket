<?php
//TODO 将 127.0.0.1 替换为服务的真实 Host, 即可
$host     = '127.0.0.1';
/**
 * 动态的路径名称
 */
$realPath = '';
/**
 * 即将收集的数据
 */
$allData  = array();
function getDir($path, $realPath, $host, &$allData)
{
    if (is_dir($path)) {
        $dir = scandir($path);
        foreach ($dir as $value) {
            $sub_path = $path . '/' . $value;
            if ($value == '.' || $value == '..') {
                continue;
            } elseif (is_dir($sub_path)) {
                $realPath = $value;
                getDir($sub_path, $realPath, $host, $allData);
            } else {
                if ($value == 'web.html') {
                    $url  = 'http://'.$host.':8088/' . $path . '/' . $value;
                    $allData[$realPath][] = [$value, $url];
                    echo "<a target='_blank' href='$url'>🤖️️点击开始聊天👉: " . $value . '</a><br/>';
                }
            }
        }
    }
}

/**
 * 当前路径名称
 */
$path = './';

/**
 * 立刻调用
 */
getDir($path, $realPath, $host, $allData);
