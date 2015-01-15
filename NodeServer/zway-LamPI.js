/*** LamPI Node Module *********************************************
Author: M. Westenberg (mw12554@hotmail.com)

******************************************************************************/

var sys  = require("sys");
var S = require("string");
var app = require('express')();
var http = require('http').createServer(app);
var io = require('socket.io')(http);
// var ws = require("nodejs-websocket");

var url = require("url");
var net  = require("net");

var HOST = "0.0.0.0";
var PORT = "5000";

// HTTP Server

app.get('/', function(req, res){

  //send the index.html file for all requests
  // res.sendFile(__dirname + '/index.html');
	res.sendFile(__dirname + '/index.html');

});

io.on('connection', function(socket){
  console.log('a user connected');
});

io.on('upgrade', function(socket){
  console.log('a user upgrade');
});


http.listen(8888, function(){
  console.log('socket.io listening on *:8888');
});




// Sockets Server, listen for incoming connections

//var server = net.createServer(function(socket) { //'connection' listener
var server = net.createServer(function(socket) { //'connection' listener
	
	socket.name = socket.remoteAddress + ":" + socket.remotePort;
	console.log('server connected to: '+socket.name);
	//broadcast
    // Write an message back to the requestor
	//socket.write('hello\r\n');
  
	socket.on('end', function() {
		console.log('server disconnected');
	});
	
	socket.on('text', function(txt) {
		console.log('server received text: '+txt);
	});
  
	socket.on('data', function(data) {
		console.log("data received: "+ data);
  		
	});
	
	socket.on('upgrade', function(request, socket, head) {
		console.log("data received: "+ request);
  		
	});
	
	socket.on('connect', function() {
		console.log("Connection Established ");
  		
	});
	
});

// Bind to well-known LamPI port, this will be done at startup of server
server.listen(PORT, HOST, function() { //'listening' listener
								   
	console.log('server bound to addr:port: '+HOST+":"+PORT);
});

function init() {
	// empty for the moment
}

function broadcast(server, msg) {
    server.connections.forEach(function (conn) {
        // conn.sendText(msg)
		console.log("Connected to: "+conn.socket.remoteAddress);
    })
}

//	Get the time
//
function getTime() {
  var date = new Date();
  return S(date.getHours()).padLeft(2,'0').s + ':' + S(date.getMinutes()).padLeft(2,'0').s + ':' + S(date.getSeconds()).padLeft(2,'0').s ;
}

// MAIN
var i=0;
var id = setInterval ( function() { console.log('Loop '+getTime()); i++; }, 10000 );



