<?php
use Workerman\Worker;
use Firebase\JWT\JWT;
require_once '../Workerman-master/Autoloader.php';
require('./vendor/autoload.php');
$worker = new Worker('websocket://0.0.0.0:8686');
$worker->count = 1;
$users = [];
$userConn = [];
$worker->onConnect = function($connection){
    $connection->onWebSocketConnect = function ($connection, $http_header) {
    // 解析token 获取当前的用户信息
    // 解析令牌 
    global $users, $worker, $userConn;
    try
    {  
       
        $key = "example_key";
        $data = JWT::decode($_GET['token'], $key, array('HS256'));
        $connection->uid = $data->id;
        $connection->uname = $data->username;
        // $connection->logintime = $data->iat;
        // 保存用户到数组中
        $users[] = ['id'=>$data->id,'username'=>$data->username];
         // 把当前这个客户端的对象保存到数组中，用户ID是下标
        $userConn[$data->id] = $connection;
        // 告诉服务器端所有上线的用户
        $connection->send(json_encode(array('type'=>'users','users'=>$users)));
        // 若果用户连接成功就告诉其他客户端
        foreach($worker->connections as $c){
            // 告诉出自己以外的所有客户端
            if($c!=$connection)
                $c->send(json_encode([
                    'type'=>'newUser',
                    'newUser'=>array('id'=>$data->id,'username'=>$data->username)
                ]));
        }
    }
    catch(  \Firebase\JWT\ExpiredException $e)
    {   
        
        $connection->close();
    }
    catch( \Exception $e)
    {
        $connection->close();
    }
    // $connection->id = $_GET['id'];
    // 保存当前用户信息
    
    
}; 
};


// 接收消息
$worker->onMessage = function($connection, $data) {
    global $worker;
    // echo $data;
    /* 从消息中解析出第一个:前面的内容，以判断是群发还是单发 */
    // 根据 : 将字符串转成数组
    $ret = explode(':', $data);
    var_dump($ret);
    // 取出第一个元素（第一个:前面的内容）
    $code = $ret[0];
    // 去掉第一个:前面的内容
    unset($ret[0]);
    // 再把数组拼回字符串
    $rawData = implode(':', $ret);
        // 判断第一个元素
        // 引入保存所有客户端与用户ID对应关系的数组
        global $userConn;
        // 根据用户ID找到对应的客户端对象，
        // 然后调用这个对象的 send 方法给这个客户端发消息
        $userConn[$code]->send(json_encode([
            'type'=>'message',
            'to'=>$code,
            'message'=>$rawData,
            'send'=>$connection->uid,
        ]));



        
    // 群发时
    // if($code == 'all')
    // {
    //     // 群发
    //     // 循环所有的客户端，给它们发消息
    //     foreach($worker->connections as $c)
    //     {
    //         $c->send(json_encode([
    //             'type'=>'message',
    //             'message'=>$rawData
    //         ]));
    //     }
    // }
    // else
    // {
       
};
//当有人断开连接时调用这个函数
$worker->onClose = function ($connection){

    global $users,$worker;

//    告诉所有用户 退出聊天的客户端的id
    foreach($worker->connections as $c){
        if($c!==$connection){
            $c->send(json_encode(array(
                'type'=>'logoutUser',
                'logoutUserId'=>$connection->uid
            )));
        }
    }
//    删除保存的客户端信息
    for($i=0;$i<count($users);$i++){
        if($users[$i]['id']==$connection->uid){
            unset($users[$i]);
        }
    }
    $users = array_values($users);


};

Worker::runAll();
