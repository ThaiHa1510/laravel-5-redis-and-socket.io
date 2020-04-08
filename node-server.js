const express=require('express');
const io=require('socket.io');
const redis=require('redis');
const server=require('htpp').createServer(app);
server.listen(8000);
const redisClient=redis.createClient();
var user=listenUser={};
io.listen(server);
app.use(express.static(__dirname+'public'));
io.on('connection',function(socket){
    socket.on('new chatbox',function(user){
        user[user.id]=socket;
        listenUser['id']=user.id;
        listenUser['name']=user.name;
        redisClient.subscribe('message_in'+user[user.id]);
        redisClient.on("message_in"+ user[user.id], function(channel, message) {
           // console.log("mew message in queue "+ message + "channel");
            socket.emit('message', message);
          });
    });

    socket.on('send msg',function(user){
        if(user[user.id] || listenUser[user.id] ){
            return
        }
        user[user.sendTo].emit('new message',{from:user.username,message:user.message});
        user[user.username].emit('revice message',{from:user.username,message:user.message});
        

    });
    socket.on('disconnect',function(user){
        if(user[user.username]){
            delete user[user.username];
            redisClient.quit();
        }
        else
        return;
    });
})


const app=express();
