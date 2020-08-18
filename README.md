# hello  socket  For  PHP


## 1、修改 web.html 文件中的host
```js
    //TODO 将 127.0.0.1 替换为服务的真实 Host, 即可
    let host = '127.0.0.1';
```


## 2、修改 index.php 文件中的host
```php
    //TODO 将 127.0.0.1 替换为服务的真实 Host, 即可
    $host     = '127.0.0.1';
```


## 3、启动服务：访问 host + 8088 即可
```php
   1、启动服务端：php SocketService.php
   
   2、启动客户端： php -S 0.0.0.0:8088 -t ./ &
   
   3、访问host+8088：
   
   🤖️️ 点击开始聊天 👉: web.html
   
   点击后，即可开始聊天，balabala ...
```

