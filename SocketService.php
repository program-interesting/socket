<?php

/**
 * Class SocketService
 * @User : lidi
 * @Email: lucklidi@126.com
 * @Date : 2020-08-18
 */
class SocketService
{
    private $address = '0.0.0.0';
    private $port    = 8083;
    private $_sockets;

    public function __construct($address = '', $port = '')
    {
        if (!empty($address)) {
            $this->address = $address;
        }
        if (!empty($port)) {
            $this->port = $port;
        }
    }

    /**
     * @throws \Exception
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function service()
    {
        /**
         * 获取tcp协议号码
         */
        $tcp  = getprotobyname("tcp");
        $sock = socket_create(AF_INET, SOCK_STREAM, $tcp);
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($sock < 0) {
            throw new Exception("failed to create socket: " . socket_strerror($sock) . "\n");
        }
        socket_bind($sock, $this->address, $this->port);
        socket_listen($sock, $this->port);
        echo "listen on $this->address $this->port ... \n";
        $this->_sockets = $sock;
    }

    /**
     * @throws \Exception
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function run()
    {
        $this->service();
        $clients[] = $this->_sockets;
        while (true) {
            $changes = $clients;
            $write   = null;
            $except  = null;
            //当select处于等待时,两个客户端中甲先发数据来,则socket_select会在$changes中保留甲的socket并往下运行,
            //另一个客户端的socket就被丢弃了,所以再次循环时,变成只监听甲了,
            //这个可以在新循环中把所有链接的客户端socket再次加进$changes中,
            //则可以避免本程序的这个逻辑错误
            /** socket_select是阻塞，有数据请求才处理，否则一直阻塞
             * 此处$changes会读取到当前活动的连接
             * 比如执行socket_select前的数据如下(描述socket的资源ID)：
             * $socket = Resource id #4
             * $changes = Array
             *       (
             *           [0] => Resource id #5 //客户端1
             *           [1] => Resource id #4 //server绑定的端口的socket资源
             *       )
             * 调用socket_select之后，此时有两种情况：
             * 情况一：如果是新客户端2连接，那么 $changes = array([1] => Resource id #4),此时用于接收新客户端2连接
             * 情况二：如果是客户端1(Resource id #5)发送消息，那么$changes = array([1] => Resource id #5)，用户接收客户端1的数据
             *
             * 通过以上的描述可以看出，socket_select有两个作用，这也是实现了IO复用
             * 1、新客户端来了，通过 Resource id #4 介绍新连接，如情况一
             * 2、已有连接发送数据，那么实时切换到当前连接，接收数据，如情况二*/
            socket_select($changes, $write, $except, null);
            foreach ($changes as $key => $_sock) {
                if ($this->_sockets == $_sock) { //判断是不是新接入的socket
                    if (($newClient = socket_accept($_sock)) === false) {
                        die('failed to accept socket: ' . socket_strerror($_sock) . "\n");
                    }
                    $line = trim(socket_read($newClient, 1024));
                    if ($line === false) {
                        socket_shutdown($newClient);
                        socket_close($newClient);
                        continue;
                    }
                    $this->handshaking($newClient, $line);
                    //获取client ip
                    socket_getpeername($newClient, $ip);
                    $clients[$ip] = $newClient;
                    echo "Client ip:{$ip}  \n";
                    echo "Client msg:{$line} \n";
                } else {
                    $byte = socket_recv($_sock, $buffer, 2048, 0);
                    if ($byte < 7) continue;
                    $msg = $this->message($buffer);
                    /**
                     * 这里业务代码：🌹
                     */
                    echo "{$key} client msg:", $msg, "\n";
                    fwrite(STDOUT, 'Please input a argument:');
                    $response = trim(fgets(STDIN));
                    $response = "管理员💬：$response";
                    $this->send($_sock, $response);
                    //echo "{$key} response to Client:" . $response, "\n";
                }
            }
        }
    }

    /**
     * 握手处理
     * @param $newClient
     * @param $line
     * @return int   接收到的信息
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function handshaking($newClient, $line)
    {
        $headers = [];
        $lines   = preg_split("/\r\n/", $line);
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/^(\S+): (.*)$/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        $secKey    = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade   = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->address\r\n" .
            "WebSocket-Location: ws://$this->address:$this->port/websocket/websocket\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        return socket_write($newClient, $upgrade, strlen($upgrade));
    }

    /**
     * 解析接收数据
     * @param $buffer
     * @return string|null
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function message($buffer)
    {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data  = substr($buffer, 8);
        } elseif ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data  = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data  = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    /**
     * 发送数据
     * @param $newClient //新接入的socket
     * @param $msg       //要发送的数据
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function send($newClient, $msg)
    {
        $msg = $this->frame($msg);
        socket_write($newClient, $msg, strlen($msg));
    }

    /**
     * 字节流输出
     * @param $s
     * @return string
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function frame($s)
    {
        $a = str_split($s, 125);
        if (count($a) == 1) {
            return "\x81" . chr(strlen($a[0])) . $a[0];
        }
        $ns = "";
        foreach ($a as $o) {
            $ns .= "\x81" . chr(strlen($o)) . $o;
        }
        return $ns;
    }

    /**
     * 关闭socket
     * @User : lidi
     * @Email: lucklidi@126.com
     * @Date : 2020-08-18
     */
    public function close()
    {
        return socket_close($this->_sockets);
    }
}

$sock = new SocketService();

$sock->run();